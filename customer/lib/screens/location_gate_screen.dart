import 'package:flutter/material.dart';

import '../services/delivery_zone_service.dart';
import '../services/location_service.dart';
import 'common/location_disabled_screen.dart';
import 'common/location_picker_screen.dart';
import 'common/service_unavailable_screen.dart';

enum _GateStage {
  checking,
  locationDisabled,
  picking,
  resolvingPick,
  unavailable,
  resolved,
}

/// Mandatory location gate shown before the app's real Home/Login decision.
/// Runs in front of [destinationBuilder]: if a location is already cached,
/// this is a zero-friction pass-through; otherwise it walks the user through
/// enabling GPS (if off), picking a location, and checking it against the
/// admin's configured delivery zones before handing off.
class LocationGateScreen extends StatefulWidget {
  const LocationGateScreen({super.key, required this.destinationBuilder});

  final WidgetBuilder destinationBuilder;

  @override
  State<LocationGateScreen> createState() => _LocationGateScreenState();
}

class _LocationGateScreenState extends State<LocationGateScreen> {
  final LocationService _locationService = LocationService();
  final DeliveryZoneService _zoneService = DeliveryZoneService();

  _GateStage _stage = _GateStage.checking;
  PickedLocation? _candidate;

  @override
  void initState() {
    super.initState();
    _check();
  }

  Future<void> _check() async {
    final saved = await _locationService.getSavedLocation();
    if (!mounted) return;
    if (saved != null) {
      setState(() => _stage = _GateStage.resolved);
      return;
    }

    final enabled = await _locationService.isLocationServiceEnabled();
    if (!mounted) return;
    setState(() {
      _stage = enabled ? _GateStage.picking : _GateStage.locationDisabled;
    });
  }

  void _onLocationEnabled() {
    setState(() => _stage = _GateStage.picking);
  }

  Future<void> _onCandidatePicked(PickedLocation picked) async {
    setState(() {
      _candidate = picked;
      _stage = _GateStage.resolvingPick;
    });

    final serviceable = await _zoneService.isServiceable(
      picked.latitude,
      picked.longitude,
    );
    if (!mounted) return;

    if (!serviceable) {
      setState(() => _stage = _GateStage.unavailable);
      return;
    }

    await _locationService.saveLocation(
      picked.city,
      picked.latitude,
      picked.longitude,
      address: picked.address,
    );
    if (!mounted) return;
    setState(() => _stage = _GateStage.resolved);
  }

  void _tryDifferentLocation() {
    setState(() {
      _candidate = null;
      _stage = _GateStage.picking;
    });
  }

  @override
  Widget build(BuildContext context) {
    switch (_stage) {
      case _GateStage.checking:
        return const Scaffold(
          body: Center(child: CircularProgressIndicator()),
        );
      case _GateStage.locationDisabled:
        return LocationDisabledScreen(onEnabled: _onLocationEnabled);
      case _GateStage.picking:
        return LocationPickerScreen(
          onPicked: _onCandidatePicked,
          isLocationServiceDisabled: () {
            setState(() => _stage = _GateStage.locationDisabled);
          },
        );
      case _GateStage.resolvingPick:
        return const Scaffold(
          body: Center(child: CircularProgressIndicator()),
        );
      case _GateStage.unavailable:
        return ServiceUnavailableScreen(
          onTryDifferentLocation: _tryDifferentLocation,
        );
      case _GateStage.resolved:
        return widget.destinationBuilder(context);
    }
  }
}
