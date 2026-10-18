import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/location_service.dart';
import '../../theme/foodflow_theme.dart';

class PickedLocation {
  const PickedLocation({
    required this.city,
    required this.address,
    required this.latitude,
    required this.longitude,
  });

  final String city;
  final String address;
  final double latitude;
  final double longitude;
}

/// A mandatory, full-screen location picker (no back/close affordance) used
/// by [LocationGateScreen]. Lets the user detect their current GPS location
/// or search for an address manually; either path resolves via [onPicked].
class LocationPickerScreen extends StatefulWidget {
  const LocationPickerScreen({
    super.key,
    required this.onPicked,
    this.isLocationServiceDisabled,
  });

  final ValueChanged<PickedLocation> onPicked;

  /// Called if GPS is found disabled mid-flow (e.g. toggled off after the
  /// gate's initial check). Caller decides how to handle it (e.g. show the
  /// location-disabled screen).
  final VoidCallback? isLocationServiceDisabled;

  @override
  State<LocationPickerScreen> createState() => _LocationPickerScreenState();
}

class _LocationPickerScreenState extends State<LocationPickerScreen> {
  final LocationService _locationService = LocationService();
  final TextEditingController _searchController = TextEditingController();
  Timer? _debounce;
  List<Map<String, dynamic>> _suggestions = [];
  bool _isSearching = false;
  bool _isUsingCurrentLocation = false;
  String? _error;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String query) {
    _debounce?.cancel();
    if (query.trim().length < 3) {
      setState(() => _suggestions = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 450), () async {
      setState(() => _isSearching = true);
      final results = await _locationService.getLocationSuggestions(query);
      if (!mounted) return;
      setState(() {
        _suggestions = results;
        _isSearching = false;
      });
    });
  }

  Future<void> _useCurrentLocation() async {
    if (_isUsingCurrentLocation) return;
    setState(() {
      _isUsingCurrentLocation = true;
      _error = null;
    });
    try {
      final enabled = await _locationService.isLocationServiceEnabled();
      if (!enabled) {
        widget.isLocationServiceDisabled?.call();
        return;
      }
      final position = await _locationService.getCurrentLocation();
      if (position == null) {
        setState(() => _error = 'Unable to fetch current location.');
        return;
      }
      final address = await _locationService.getAddressFromLatLng(
        position.latitude,
        position.longitude,
      );
      final city = address?['city']?.trim();
      final label = address?['address']?.trim();
      widget.onPicked(PickedLocation(
        city: city?.isNotEmpty == true ? city! : 'Current location',
        address: label?.isNotEmpty == true ? label! : 'Current location',
        latitude: position.latitude,
        longitude: position.longitude,
      ));
    } finally {
      if (mounted) setState(() => _isUsingCurrentLocation = false);
    }
  }

  void _selectSuggestion(Map<String, dynamic> suggestion) {
    final lat = suggestion['lat'];
    final lng = suggestion['lng'];
    if (lat is! double || lng is! double) return;
    final city = (suggestion['city'] ?? '').toString();
    final displayName = (suggestion['display_name'] ?? city).toString();
    widget.onPicked(PickedLocation(
      city: city.isNotEmpty ? city : displayName,
      address: displayName.isNotEmpty ? displayName : city,
      latitude: lat,
      longitude: lng,
    ));
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      child: Scaffold(
        backgroundColor: FoodFlowTheme.canvas,
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Select your delivery location',
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'We use this to show restaurants that deliver to you.',
                  style: TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 20),
                TextField(
                  controller: _searchController,
                  onChanged: _onSearchChanged,
                  decoration: InputDecoration(
                    hintText: 'Search area, street, or landmark',
                    prefixIcon: const Icon(Icons.search_rounded),
                    suffixIcon: _isSearching
                        ? const Padding(
                            padding: EdgeInsets.all(14),
                            child: SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            ),
                          )
                        : null,
                    filled: true,
                    fillColor: Colors.white,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: const BorderSide(color: FoodFlowTheme.line),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: const BorderSide(color: FoodFlowTheme.line),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: BorderSide(
                        color: FoodFlowTheme.brandPrimary(context),
                        width: 1.4,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                _ActionTile(
                  icon: Icons.my_location_rounded,
                  label: _isUsingCurrentLocation
                      ? 'Detecting your location...'
                      : 'Use current location',
                  isLoading: _isUsingCurrentLocation,
                  onTap: _useCurrentLocation,
                ),
                if (_error != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    _error!,
                    style: const TextStyle(
                      color: FoodFlowTheme.danger,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                Expanded(
                  child: _suggestions.isEmpty
                      ? const SizedBox.shrink()
                      : ListView.separated(
                          itemCount: _suggestions.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 8),
                          itemBuilder: (context, index) {
                            final suggestion = _suggestions[index];
                            return _SuggestionTile(
                              title: (suggestion['city'] ?? '').toString(),
                              subtitle:
                                  (suggestion['display_name'] ?? '').toString(),
                              onTap: () => _selectSuggestion(suggestion),
                            );
                          },
                        ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.isLoading = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: isLoading ? null : onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: FoodFlowTheme.line),
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: primary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: isLoading
                    ? Padding(
                        padding: const EdgeInsets.all(9),
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: primary,
                        ),
                      )
                    : Icon(icon, color: primary, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SuggestionTile extends StatelessWidget {
  const _SuggestionTile({
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: FoodFlowTheme.line),
          ),
          child: Row(
            children: [
              const Icon(Icons.location_on_outlined,
                  color: FoodFlowTheme.muted, size: 20),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title.isEmpty ? subtitle : title,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (subtitle.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
