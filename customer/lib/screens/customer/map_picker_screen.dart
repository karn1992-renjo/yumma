import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../models/address.dart' as app_address;
import '../../widgets/common/lucide_icon.dart';

class MapPickerScreen extends StatefulWidget {
  const MapPickerScreen({super.key, this.address});

  final app_address.Address? address;

  @override
  State<MapPickerScreen> createState() => _MapPickerScreenState();
}

class _MapPickerScreenState extends State<MapPickerScreen> {
  static const LatLng _fallback = LatLng(28.6139, 77.2090);
  late LatLng _selected;

  @override
  void initState() {
    super.initState();
    final address = widget.address;
    _selected = address?.latitude != null && address?.longitude != null
        ? LatLng(address!.latitude!, address.longitude!)
        : _fallback;
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
        createdAt: current?.createdAt ?? DateTime.now(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pick delivery pin'),
        actions: [
          TextButton(
            onPressed: _returnSelection,
            child: const Text('Use pin'),
          ),
        ],
      ),
      body: GoogleMap(
        initialCameraPosition: CameraPosition(target: _selected, zoom: 16),
        onTap: (position) => setState(() => _selected = position),
        markers: {
          Marker(
            markerId: const MarkerId('delivery_pin'),
            position: _selected,
            draggable: true,
            onDragEnd: (position) => setState(() => _selected = position),
          ),
        },
        myLocationButtonEnabled: true,
        zoomControlsEnabled: false,
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _returnSelection,
        icon: const AppIcon(AppIcons.check),
        label: const Text('Use pin'),
      ),
    );
  }
}
