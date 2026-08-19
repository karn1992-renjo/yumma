import 'package:flutter/material.dart';
import '../services/location_service.dart';
import '../theme/foodflow_theme.dart';

class LocationRequiredScreen extends StatefulWidget {
  final String nextRoute;

  const LocationRequiredScreen({
    Key? key,
    required this.nextRoute,
  }) : super(key: key);

  @override
  State<LocationRequiredScreen> createState() => _LocationRequiredScreenState();
}

class _LocationRequiredScreenState extends State<LocationRequiredScreen> {
  final LocationService _locationService = LocationService();
  bool _isLoading = true;
  bool _isSaving = false;
  Map<String, dynamic>? _savedLocation;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadSavedLocation();
  }

  Future<void> _loadSavedLocation() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    bool shouldUpdate = false;
    try {
      final location = await _locationService.getSavedLocation();
      if (mounted) {
        setState(() {
          _savedLocation = location;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Unable to load saved location.';
        });
      }
    } finally {
      shouldUpdate = mounted;
    }

    if (shouldUpdate) {
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _useSavedLocation() async {
    if (_savedLocation == null) {
      return;
    }
    _navigateNext();
  }

  Future<void> _useCurrentLocation() async {
    setState(() {
      _isSaving = true;
      _errorMessage = null;
    });

    try {
      final position = await _locationService.getCurrentLocation();
      if (position == null) {
        if (!mounted) return;
        setState(() {
          _errorMessage =
              'Location permission or service is required to continue.';
        });
        return;
      }

      final address = await _locationService.getAddressFromLatLng(
        position.latitude,
        position.longitude,
      );

      final city = address?['city'] ?? 'Current location';
      await _locationService.saveLocation(
        city,
        position.latitude,
        position.longitude,
      );

      _navigateNext();
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Unable to get current location: $e';
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _isSaving = false;
        });
      }
    }
  }

  void _navigateNext() {
    Navigator.pushReplacementNamed(context, widget.nextRoute);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Restaurant Location'),
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Restaurant location tools',
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Your restaurant can continue without updating device location. Use this only when you want to refresh a saved map point.',
                    style: TextStyle(
                      fontSize: 17,
                      color: FoodFlowTheme.muted,
                      fontWeight: FontWeight.w600,
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 24),
                  if (_savedLocation != null) ...[
                    _buildSavedLocationCard(),
                    const SizedBox(height: 16),
                  ],
                  ElevatedButton.icon(
                    icon: const Icon(Icons.my_location),
                    label: const Text('Use Current Location'),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                    ),
                    onPressed: _isSaving ? null : _useCurrentLocation,
                  ),
                  const SizedBox(height: 12),
                  if (_savedLocation != null)
                    OutlinedButton.icon(
                      icon: const Icon(Icons.location_on_outlined),
                      label: const Text('Use Saved Address'),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 16),
                      ),
                      onPressed: _isSaving ? null : _useSavedLocation,
                    ),
                  if (_savedLocation == null) ...[
                    const SizedBox(height: 16),
                    const Text(
                      'No device location is saved yet. This is optional for existing restaurants.',
                      style: TextStyle(
                        fontSize: 15,
                        color: FoodFlowTheme.muted,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  if (_errorMessage != null) ...[
                    const SizedBox(height: 24),
                    Text(
                      _errorMessage!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ],
                  const Spacer(),
                  OutlinedButton(
                    onPressed: _isSaving ? null : _navigateNext,
                    child: const Text('Continue without updating'),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed: _isSaving ? null : _loadSavedLocation,
                    child: const Text('Reload saved location'),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildSavedLocationCard() {
    final address = _savedLocation?['city']?.toString() ?? 'Saved location';
    final lat = _savedLocation?['lat']?.toString() ?? '-';
    final lng = _savedLocation?['lng']?.toString() ?? '-';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Saved address',
              style: TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 17,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              address,
              style: const TextStyle(
                fontSize: 16,
                color: FoodFlowTheme.inkSoft,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Latitude: $lat • Longitude: $lng',
              style: const TextStyle(fontSize: 14, color: FoodFlowTheme.muted),
            ),
          ],
        ),
      ),
    );
  }
}
