import 'package:flutter/material.dart';

import '../../../../config/api_constants.dart';
import '../../../../models/address.dart' as app_address;
import '../../../../services/api_service.dart';
import '../../../../services/location_service.dart';
import '../theme/v2_theme.dart';
import 'v2_anim.dart';
import 'v2_glass.dart';

class V2PickedLocation {
  V2PickedLocation({
    required this.city,
    required this.address,
    required this.lat,
    required this.lng,
  });
  final String city;
  final String address;
  final double lat;
  final double lng;
}

/// Glass bottom sheet mirroring the production location picker: use current
/// location, saved addresses, add a new one.
Future<V2PickedLocation?> showV2LocationSheet(BuildContext context) {
  return showModalBottomSheet<V2PickedLocation>(
    context: context,
    backgroundColor: Colors.transparent,
    isScrollControlled: true,
    builder: (_) => V2Theme(
      palette: V2Palette.of(v2ModeNotifier.value),
      child: const _LocationSheet(),
    ),
  );
}

class _LocationSheet extends StatefulWidget {
  const _LocationSheet();

  @override
  State<_LocationSheet> createState() => _LocationSheetState();
}

class _LocationSheetState extends State<_LocationSheet> {
  final ApiService _api = ApiService();
  final LocationService _location = LocationService();

  bool _loading = true;
  bool _locating = false;
  List<app_address.Address> _addresses = const [];

  @override
  void initState() {
    super.initState();
    _loadAddresses();
  }

  Future<void> _loadAddresses() async {
    setState(() => _loading = true);
    try {
      final res = await _api.get(ApiConstants.addresses);
      final data = res is Map ? res['data'] : res;
      final list = data is List
          ? data
          : (data is Map && data['data'] is List ? data['data'] as List : const []);
      _addresses = list
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

  Future<void> _useCurrent() async {
    setState(() => _locating = true);
    try {
      final pos = await _location.getCurrentLocation();
      if (pos == null) {
        _toast('Could not get your location');
        return;
      }
      final parts =
          await _location.getAddressFromLatLng(pos.latitude, pos.longitude);
      final city = (parts?['city'] ?? '').trim();
      final address = (parts?['address'] ?? city).trim();
      if (!mounted) return;
      Navigator.of(context).pop(V2PickedLocation(
        city: city.isEmpty ? 'Current location' : city,
        address: address.isEmpty ? 'Current location' : address,
        lat: pos.latitude,
        lng: pos.longitude,
      ));
    } catch (_) {
      _toast('Could not get your location');
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  void _pick(app_address.Address a) {
    final lat = a.latitude, lng = a.longitude;
    if (lat == null || lng == null) {
      _toast('This address has no map location');
      return;
    }
    Navigator.of(context).pop(V2PickedLocation(
      city: a.city.isNotEmpty ? a.city : a.name,
      address: a.address.isNotEmpty ? a.address : a.name,
      lat: lat,
      lng: lng,
    ));
  }

  void _toast(String m) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(m)));
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      margin: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: p.isDark ? const Color(0xFF141A29) : Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: p.glassBorder),
        boxShadow: [
          BoxShadow(color: p.shadow, blurRadius: 30, offset: const Offset(0, 16)),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 10),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: p.inkFaint.withOpacity(0.5),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 14, 8),
              child: Row(
                children: [
                  Text(
                    'Delivery location',
                    style: TextStyle(
                      color: p.ink,
                      fontSize: 16,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const Spacer(),
                  V2Tappable(
                    onTap: () => Navigator.of(context).pop(),
                    child: Icon(Icons.close_rounded, color: p.inkFaint, size: 22),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: V2Tappable(
                onTap: _locating ? null : _useCurrent,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 14),
                  decoration: BoxDecoration(
                    color: p.accent.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: p.accent.withOpacity(0.4)),
                  ),
                  child: Row(
                    children: [
                      _locating
                          ? SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: p.accent),
                            )
                          : Icon(Icons.my_location_rounded,
                              size: 20, color: p.accent),
                      const SizedBox(width: 12),
                      Text(
                        'Use my current location',
                        style: TextStyle(
                          color: p.accent,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 6),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'SAVED ADDRESSES',
                  style: TextStyle(
                    color: p.inkFaint,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.8,
                  ),
                ),
              ),
            ),
            Flexible(
              child: _loading
                  ? Padding(
                      padding: const EdgeInsets.all(24),
                      child: CircularProgressIndicator(color: p.accent),
                    )
                  : _addresses.isEmpty
                      ? Padding(
                          padding: const EdgeInsets.fromLTRB(20, 4, 20, 14),
                          child: Text(
                            'No saved addresses yet.',
                            style:
                                TextStyle(color: p.inkFaint, fontSize: 12.5),
                          ),
                        )
                      : ListView.builder(
                          shrinkWrap: true,
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          itemCount: _addresses.length,
                          itemBuilder: (_, i) {
                            final a = _addresses[i];
                            return V2Tappable(
                              onTap: () => _pick(a),
                              child: Container(
                                margin: const EdgeInsets.symmetric(
                                    horizontal: 6, vertical: 4),
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 12, vertical: 12),
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(14),
                                  border:
                                      Border.all(color: p.glassBorder),
                                ),
                                child: Row(
                                  children: [
                                    Icon(
                                      a.isDefault
                                          ? Icons.home_rounded
                                          : Icons.location_on_rounded,
                                      size: 18,
                                      color: p.inkSoft,
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            a.name.isNotEmpty
                                                ? a.name
                                                : a.city,
                                            style: TextStyle(
                                              color: p.ink,
                                              fontSize: 13.5,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          const SizedBox(height: 2),
                                          Text(
                                            [a.address, a.city, a.pincode]
                                                .where((e) => e.trim().isNotEmpty)
                                                .join(', '),
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: TextStyle(
                                              color: p.inkFaint,
                                              fontSize: 11.5,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 6, 16, 12),
              child: V2Tappable(
                onTap: () async {
                  await Navigator.of(context).pushNamed('/addresses/add');
                  _loadAddresses();
                },
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 13),
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: p.accent,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Text(
                    'Add a new address',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
