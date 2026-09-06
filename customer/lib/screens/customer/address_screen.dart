// lib/screens/customer/address_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

import '../../config/api_constants.dart';
import '../../models/address.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/common/app_skeleton.dart';
import '../../widgets/customer/profile_screen_chrome.dart';

class AddressScreen extends StatefulWidget {
  const AddressScreen({Key? key}) : super(key: key);

  @override
  State<AddressScreen> createState() => _AddressScreenState();
}

class _AddressScreenState extends State<AddressScreen> {
  final ApiService _api = ApiService();
  List<Address> _addresses = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadAddresses();
  }

  Future<void> _loadAddresses({bool forceRefresh = false}) async {
    if (mounted) setState(() => _isLoading = _addresses.isEmpty);

    try {
      final response = await _api.get(
        ApiConstants.addresses,
        cachePolicy: ApiCachePolicy.screen,
        cacheFirst: !forceRefresh,
        refreshCached: !forceRefresh,
        onCacheRefreshed: _applyAddresses,
      );
      _applyAddresses(response);
    } catch (e) {
      debugPrint('Load addresses error: $e');
    }

    if (mounted) setState(() => _isLoading = false);
  }

  void _applyAddresses(dynamic response) {
    if (!mounted || response is! Map || response['success'] != true) return;
    final data = response['data'];
    if (data is! List) return;
    setState(() {
      _addresses = data
          .whereType<Map>()
          .map((json) => Address.fromJson(Map<String, dynamic>.from(json)))
          .toList();
    });
  }

  Future<void> _openAddAddress() async {
    await Navigator.pushNamed(context, '/addresses/add');
    if (mounted) await _loadAddresses(forceRefresh: true);
  }

  Future<void> _openEditAddress(Address address) async {
    await Navigator.pushNamed(context, '/addresses/edit', arguments: address);
    if (mounted) await _loadAddresses(forceRefresh: true);
  }

  Future<void> _setDefaultAddress(int addressId) async {
    try {
      final response =
          await _api.post('${ApiConstants.setDefaultAddress}/$addressId');
      if (response['success'] == true) {
        await _loadAddresses(forceRefresh: true);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Default address updated')),
        );
      }
    } catch (e) {
      debugPrint('Set default error: $e');
    }
  }

  Future<void> _deleteAddress(int addressId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete address'),
        content: const Text('Are you sure you want to delete this address?'),
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
            child: const Text('Cancel'),
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
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      final response = await _api.post(ApiConstants.deleteAddress(addressId));
      if (response['success'] == true) {
        await _loadAddresses(forceRefresh: true);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Address deleted successfully')),
        );
      }
    } catch (e) {
      debugPrint('Delete address error: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: profileCanvasColor(context),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => _loadAddresses(forceRefresh: true),
          color: profileAccentColor(context),
          child: Stack(
            children: [
              ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 40),
                children: [
                  const ProfilePageTopBar(
                    title: 'Saved Addresses',
                    subtitle: 'Your delivery places',
                  ),
                  const SizedBox(height: 20),

                  // Add-address action tile (dashed)
                  _AddAddressTile(onTap: _openAddAddress),
                  const SizedBox(height: 18),

                  if (_addresses.isNotEmpty || (_isLoading == false)) ...[
                    ProfileSectionLabel(
                      title: _addresses.isEmpty
                          ? 'No places yet'
                          : '${_addresses.length} saved '
                              '${_addresses.length == 1 ? 'place' : 'places'}',
                    ),
                    const SizedBox(height: 10),
                  ],

                  if (_isLoading && _addresses.isEmpty)
                    const AppSkeletonColumn(itemCount: 3, itemHeight: 132)
                  else if (_addresses.isEmpty)
                    _EmptyAddresses(onAdd: _openAddAddress)
                  else
                    ..._addresses.map(
                      (address) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _AddressCard(
                          address: address,
                          onSetDefault: () => _setDefaultAddress(address.id),
                          onEdit: () => _openEditAddress(address),
                          onDelete: () => _deleteAddress(address.id),
                        ),
                      ),
                    ),
                ],
              ),
              if (_isLoading && _addresses.isNotEmpty)
                const Positioned(
                  left: 0,
                  right: 0,
                  top: 0,
                  child: LinearProgressIndicator(minHeight: 2),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

IconData _iconForAddress(String name) {
  final n = name.toLowerCase();
  if (n.contains('home') || n.contains('house')) return LucideIcons.house;
  if (n.contains('work') || n.contains('office') || n.contains('job')) {
    return LucideIcons.briefcase;
  }
  if (n.contains('hotel') || n.contains('stay')) return LucideIcons.bed_double;
  return LucideIcons.map_pin;
}

class _AddAddressTile extends StatelessWidget {
  const _AddAddressTile({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = profileAccentColor(context);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: DottedBorderBox(
        color: accent.withOpacity(0.5),
        radius: 18,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 16),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: profileSoftColor(context),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(LucideIcons.plus, color: accent, size: 20),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Add a new address',
                      style: TextStyle(
                        color: profileTextColor(context),
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Home, work or a go-to spot',
                      style: TextStyle(
                        color: profileMutedColor(context),
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(LucideIcons.chevron_right,
                  color: profileMutedColor(context), size: 20),
            ],
          ),
        ),
      ),
    );
  }
}

/// Lightweight dashed border container (no extra package needed).
class DottedBorderBox extends StatelessWidget {
  const DottedBorderBox({
    super.key,
    required this.child,
    required this.color,
    this.radius = 16,
  });

  final Widget child;
  final Color color;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _DashedRRectPainter(color: color, radius: radius),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(radius),
        child: child,
      ),
    );
  }
}

class _DashedRRectPainter extends CustomPainter {
  _DashedRRectPainter({required this.color, required this.radius});

  final Color color;
  final double radius;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 1.4
      ..style = PaintingStyle.stroke;
    final rrect = RRect.fromRectAndRadius(
      Offset.zero & size,
      Radius.circular(radius),
    );
    final path = Path()..addRRect(rrect);
    const dash = 6.0;
    const gap = 5.0;
    for (final metric in path.computeMetrics()) {
      double d = 0;
      while (d < metric.length) {
        canvas.drawPath(
          metric.extractPath(d, (d + dash).clamp(0, metric.length)),
          paint,
        );
        d += dash + gap;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _DashedRRectPainter old) =>
      old.color != color || old.radius != radius;
}

class _AddressCard extends StatelessWidget {
  const _AddressCard({
    required this.address,
    required this.onSetDefault,
    required this.onEdit,
    required this.onDelete,
  });

  final Address address;
  final VoidCallback onSetDefault;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final accent = profileAccentColor(context);
    return ProfileSurfaceCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: profileSoftColor(context),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(_iconForAddress(address.name),
                    color: accent, size: 20),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                  address.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: profileTextColor(context),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (address.isDefault)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: profileSoftColor(context),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: accent.withOpacity(0.24)),
                  ),
                  child: Text(
                    'Default',
                    style: TextStyle(
                      color: accent,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            address.fullAddress,
            style: TextStyle(
              color: profileMutedColor(context),
              fontSize: 12.5,
              fontWeight: FontWeight.w500,
              height: 1.4,
            ),
          ),
          if (address.phone.isNotEmpty) ...[
            const SizedBox(height: 6),
            Row(
              children: [
                Icon(LucideIcons.phone,
                    size: 12, color: profileMutedColor(context)),
                const SizedBox(width: 6),
                Text(
                  address.phone,
                  style: TextStyle(
                    color: profileMutedColor(context),
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 12),
          Divider(height: 1, color: profileLineColor(context)),
          const SizedBox(height: 6),
          Row(
            children: [
              if (!address.isDefault)
                _CardAction(
                  icon: LucideIcons.circle_check,
                  label: 'Set default',
                  onTap: onSetDefault,
                ),
              const Spacer(),
              _CardAction(
                icon: LucideIcons.pencil,
                label: 'Edit',
                onTap: onEdit,
              ),
              const SizedBox(width: 4),
              _CardAction(
                icon: LucideIcons.trash_2,
                label: 'Delete',
                onTap: onDelete,
                danger: true,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CardAction extends StatelessWidget {
  const _CardAction({
    required this.icon,
    required this.label,
    required this.onTap,
    this.danger = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger
        ? Theme.of(context).colorScheme.error
        : profileButtonColor(context);
    return TextButton.icon(
      onPressed: onTap,
      style: TextButton.styleFrom(
        foregroundColor: color,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        minimumSize: Size.zero,
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        textStyle: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800),
      ),
      icon: Icon(icon, size: 15),
      label: Text(label),
    );
  }
}

class _EmptyAddresses extends StatelessWidget {
  const _EmptyAddresses({required this.onAdd});

  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return ProfileSurfaceCard(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          ProfileAccentIcon(
            icon: LucideIcons.map_pin_plus,
            size: 62,
            iconSize: 28,
            radius: 20,
          ),
          const SizedBox(height: 14),
          Text(
            'No saved addresses yet',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: profileTextColor(context),
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Add home, work or your go-to spots for faster checkout.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: profileMutedColor(context),
              fontSize: 12,
              fontWeight: FontWeight.w500,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: onAdd,
              style: FoodFlowTheme.zomatoPrimaryButton(
                color: profileButtonColor(context),
                foregroundColor: profileOnButtonColor(context),
                radius: 14,
              ),
              child: const Text('Add New Address'),
            ),
          ),
        ],
      ),
    );
  }
}
