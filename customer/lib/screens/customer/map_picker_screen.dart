import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../models/address.dart' as app_address;
import '../../theme/foodflow_theme.dart';
import '../../widgets/customer/profile_screen_chrome.dart';

class MapPickerScreen extends StatefulWidget {
  const MapPickerScreen({super.key, this.address});

  final app_address.Address? address;

  @override
  State<MapPickerScreen> createState() => _MapPickerScreenState();
}

class _MapPickerScreenState extends State<MapPickerScreen> {
  static const LatLng _fallback = LatLng(28.6139, 77.2090);
  GoogleMapController? _controller;
  late LatLng _selected;
  double _zoom = 16;

  @override
  void initState() {
    super.initState();
    final address = widget.address;
    _selected = address?.latitude != null && address?.longitude != null
        ? LatLng(address!.latitude!, address.longitude!)
        : _fallback;
  }

  @override
  void dispose() {
    _controller = null;
    super.dispose();
  }

  void _returnSelection() {
    final current = widget.address;
    Navigator.pop(
      context,
      app_address.Address(
        id: current?.id ?? 0,
        userId: current?.userId ?? 0,
        name: current?.name ?? 'Pinned location',
        address: current?.address ?? 'Pinned location',
        city: current?.city ?? '',
        state: current?.state ?? '',
        pincode: current?.pincode ?? '',
        phone: current?.phone ?? '',
        latitude: _selected.latitude,
        longitude: _selected.longitude,
        isDefault: current?.isDefault ?? false,
        distanceKm: current?.distanceKm,
        isDeliverable: current?.isDeliverable ?? true,
        deliveryStatusLabel: current?.deliveryStatusLabel,
        createdAt: current?.createdAt ?? DateTime.now(),
      ),
    );
  }

  Future<void> _zoomBy(double delta) async {
    final controller = _controller;
    if (controller == null) return;
    final nextZoom = (_zoom + delta).clamp(3.0, 21.0).toDouble();
    setState(() => _zoom = nextZoom);
    await controller.animateCamera(CameraUpdate.zoomTo(nextZoom));
  }

  Future<void> _recenterPin() async {
    final controller = _controller;
    if (controller == null) return;
    await controller
        .animateCamera(CameraUpdate.newLatLngZoom(_selected, _zoom));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.scrim,
      body: Stack(
        children: [
          Positioned.fill(
            child: GoogleMap(
              initialCameraPosition:
                  CameraPosition(target: _selected, zoom: _zoom),
              onMapCreated: (controller) => _controller = controller,
              onCameraMove: (position) {
                _selected = position.target;
                _zoom = position.zoom;
              },
              onCameraIdle: () {
                if (mounted) setState(() {});
              },
              myLocationEnabled: true,
              myLocationButtonEnabled: true,
              zoomControlsEnabled: true,
              compassEnabled: true,
              mapToolbarEnabled: false,
            ),
          ),
          IgnorePointer(
            child: Center(
              child: Transform.translate(
                offset: const Offset(0, -22),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: profileAccentColor(context),
                        shape: BoxShape.circle,
                        border: Border.all(
                            color: Theme.of(context).colorScheme.surface,
                            width: 3),
                        boxShadow: [
                          BoxShadow(
                            color: Theme.of(context)
                                .colorScheme
                                .shadow
                                .withOpacity(0.28),
                            blurRadius: 16,
                            offset: const Offset(0, 8),
                          ),
                        ],
                      ),
                      child: Icon(
                        LucideIcons.map_pin,
                        color: Theme.of(context).colorScheme.surface,
                        size: 24,
                      ),
                    ),
                    Container(
                      width: 12,
                      height: 12,
                      decoration: BoxDecoration(
                        color: Theme.of(context)
                            .colorScheme
                            .shadow
                            .withOpacity(0.22),
                        shape: BoxShape.circle,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            left: 18,
            right: 18,
            top: MediaQuery.paddingOf(context).top + 10,
            child: ProfilePageTopBar(
              title: 'Pick delivery pin',
              subtitle: 'Move the map until the pin is exact',
              actions: [
                const SizedBox(width: 12),
                ProfileRoundButton(
                  icon: LucideIcons.crosshair,
                  color: profileAccentColor(context),
                  onTap: _recenterPin,
                ),
              ],
            ),
          ),
          Positioned(
            right: 18,
            bottom: 126 + MediaQuery.paddingOf(context).bottom,
            child: Column(
              children: [
                _MapControlButton(
                  icon: LucideIcons.plus,
                  onTap: () => _zoomBy(1),
                ),
                const SizedBox(height: 10),
                _MapControlButton(
                  icon: LucideIcons.minus,
                  onTap: () => _zoomBy(-1),
                ),
              ],
            ),
          ),
          Positioned(
            left: 18,
            right: 18,
            bottom: 16 + MediaQuery.paddingOf(context).bottom,
            child: ProfileSurfaceCard(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  ProfileAccentIcon(
                    icon: LucideIcons.map_pin,
                    size: 48,
                    iconSize: 22,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Pinned location',
                          style: TextStyle(
                            color: profileTextColor(context),
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          '${_selected.latitude.toStringAsFixed(6)}, ${_selected.longitude.toStringAsFixed(6)}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: profileMutedColor(context),
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  ElevatedButton(
                    onPressed: _returnSelection,
                    style: FoodFlowTheme.zomatoPrimaryButton(
                      color: profileButtonColor(context),
                      foregroundColor: profileOnButtonColor(context),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 13,
                      ),
                      radius: 16,
                    ),
                    child: Text('Use pin'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MapControlButton extends StatelessWidget {
  const _MapControlButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Theme.of(context).colorScheme.surface,
      borderRadius: BorderRadius.circular(18),
      elevation: 4,
      shadowColor: Theme.of(context).colorScheme.shadow.withOpacity(0.18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: SizedBox(
          width: 46,
          height: 46,
          child: Icon(icon, color: profileTextColor(context), size: 24),
        ),
      ),
    );
  }
}
