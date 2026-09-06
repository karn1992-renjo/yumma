import 'package:flutter/material.dart';

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class NotificationPrefsV2 extends StatefulWidget {
  const NotificationPrefsV2({super.key});

  @override
  State<NotificationPrefsV2> createState() => _NotificationPrefsV2State();
}

class _NotificationPrefsV2State extends State<NotificationPrefsV2> {
  final ApiService _api = ApiService();
  bool _loading = true;
  bool _orderUpdates = true;
  bool _offers = true;
  String? _saving;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await _api.get(ApiConstants.notificationPreferences);
      final data = res is Map ? res['data'] : null;
      if (data is Map && mounted) {
        setState(() {
          _orderUpdates = data['notify_order_updates'] != false;
          _offers = data['notify_offers_promotions'] != false;
        });
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _update(String key, bool value) async {
    setState(() {
      _saving = key;
      if (key == 'notify_order_updates') {
        _orderUpdates = value;
      } else {
        _offers = value;
      }
    });
    try {
      await _api.put(ApiConstants.notificationPreferences, data: {key: value});
    } catch (_) {
      if (mounted) {
        setState(() {
          if (key == 'notify_order_updates') {
            _orderUpdates = !value;
          } else {
            _offers = !value;
          }
        });
      }
    } finally {
      if (mounted) setState(() => _saving = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      title: 'Notification Preferences',
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 30),
              children: [
                GlassPanel(
                  radius: 20,
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Column(
                    children: [
                      _tile(
                        context,
                        Icons.delivery_dining_rounded,
                        'Order updates',
                        'Status of your active orders',
                        _orderUpdates,
                        _saving == 'notify_order_updates',
                        (v) => _update('notify_order_updates', v),
                      ),
                      Divider(height: 1, color: p.glassBorder),
                      _tile(
                        context,
                        Icons.local_offer_rounded,
                        'Offers & promotions',
                        'Deals, coupons and rewards',
                        _offers,
                        _saving == 'notify_offers_promotions',
                        (v) => _update('notify_offers_promotions', v),
                      ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _tile(BuildContext context, IconData icon, String title, String sub,
      bool value, bool busy, ValueChanged<bool> onChanged) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: p.inkSoft.withOpacity(0.12),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(icon, size: 19, color: p.inkSoft),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                Text(sub,
                    style: TextStyle(color: p.inkFaint, fontSize: 11.5)),
              ],
            ),
          ),
          busy
              ? SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                      strokeWidth: 2, color: p.accent),
                )
              : Switch.adaptive(
                  value: value,
                  onChanged: onChanged,
                  activeColor: p.accent,
                ),
        ],
      ),
    );
  }
}
