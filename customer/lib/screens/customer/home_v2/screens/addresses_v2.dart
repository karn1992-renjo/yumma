import 'package:flutter/material.dart';

import '../../../../config/api_constants.dart';
import '../../../../models/address.dart' as app_address;
import '../../../../services/api_service.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class AddressesV2 extends StatefulWidget {
  const AddressesV2({super.key});

  @override
  State<AddressesV2> createState() => _AddressesV2State();
}

class _AddressesV2State extends State<AddressesV2> {
  final ApiService _api = ApiService();
  bool _loading = true;
  List<app_address.Address> _items = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await _api.get(ApiConstants.addresses);
      final data = res is Map ? res['data'] : res;
      final list = data is List
          ? data
          : (data is Map && data['data'] is List ? data['data'] as List : const []);
      _items = list
          .whereType<Map>()
          .map((e) {
            try {
              return app_address.Address.fromJson(Map<String, dynamic>.from(e));
            } catch (_) {
              return null;
            }
          })
          .whereType<app_address.Address>()
          .toList();
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _delete(app_address.Address a) async {
    try {
      await _api.post(ApiConstants.deleteAddress(a.id), data: const {});
    } catch (_) {}
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      title: 'Saved Addresses',
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : Column(
              children: [
                Expanded(
                  child: _items.isEmpty
                      ? const V2EmptyState(
                          icon: Icons.location_off_rounded,
                          title: 'No saved addresses',
                          message: 'Add one to check out faster next time.',
                        )
                      : RefreshIndicator(
                          onRefresh: _load,
                          color: p.accent,
                          backgroundColor: p.bgMid,
                          child: ListView.separated(
                            padding: const EdgeInsets.fromLTRB(16, 10, 16, 20),
                            itemCount: _items.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(height: 12),
                            itemBuilder: (_, i) => V2Entrance(
                              delay: Duration(milliseconds: 22 * i),
                              child: _AddressCard(
                                a: _items[i],
                                onEdit: () async {
                                  await Navigator.of(context).pushNamed(
                                      '/addresses/edit',
                                      arguments: _items[i]);
                                  _load();
                                },
                                onDelete: () => _delete(_items[i]),
                              ),
                            ),
                          ),
                        ),
                ),
                Padding(
                  padding: EdgeInsets.fromLTRB(
                      16, 8, 16, MediaQuery.of(context).padding.bottom + 12),
                  child: V2Tappable(
                    onTap: () async {
                      await Navigator.of(context).pushNamed('/addresses/add');
                      _load();
                    },
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 15),
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: p.accent,
                        borderRadius: BorderRadius.circular(15),
                      ),
                      child: const Text('Add a new address',
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w900,
                              fontSize: 15)),
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}

class _AddressCard extends StatelessWidget {
  const _AddressCard({
    required this.a,
    required this.onEdit,
    required this.onDelete,
  });

  final app_address.Address a;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return GlassPanel(
      radius: 18,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(a.isDefault ? Icons.home_rounded : Icons.location_on_rounded,
                  size: 18, color: p.inkSoft),
              const SizedBox(width: 10),
              Expanded(
                child: Text(a.name.isNotEmpty ? a.name : a.city,
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w800)),
              ),
              if (a.isDefault)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: p.accent.withOpacity(0.14),
                    borderRadius: BorderRadius.circular(7),
                  ),
                  child: Text('DEFAULT',
                      style: TextStyle(
                          color: p.accent,
                          fontSize: 9,
                          fontWeight: FontWeight.w900)),
                ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            [a.address, a.city, a.pincode]
                .where((e) => e.trim().isNotEmpty)
                .join(', '),
            style: TextStyle(color: p.inkFaint, fontSize: 12, height: 1.35),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              V2Tappable(
                onTap: onEdit,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 4, vertical: 4),
                  child: Text('Edit',
                      style: TextStyle(
                          color: p.accent,
                          fontWeight: FontWeight.w800,
                          fontSize: 12.5)),
                ),
              ),
              const SizedBox(width: 16),
              V2Tappable(
                onTap: onDelete,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 4, vertical: 4),
                  child: Text('Delete',
                      style: TextStyle(
                          color: p.danger,
                          fontWeight: FontWeight.w800,
                          fontSize: 12.5)),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
